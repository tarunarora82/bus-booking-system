<?php

namespace BusBooking\Services;

use BusBooking\Core\Database;

/**
 * Booking service for handling seat reservations and waitlist
 */
class BookingService
{
    private Database $db;
    private EmailService $emailService;
    private \Redis $redis;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->emailService = new EmailService();
        $this->redis = new \Redis();
        $this->redis->connect(REDIS_HOST, REDIS_PORT);
    }

    public function getAvailability(int $scheduleId, string $date): array
    {
        $schedule = $this->db->fetchOne(
            "SELECT s.*, b.bus_number, b.capacity 
             FROM schedules s 
             JOIN buses b ON s.bus_id = b.id 
             WHERE s.id = ? AND s.status = 'active' AND b.status = 'active'",
            [$scheduleId]
        );

        if (!$schedule) {
            throw new \Exception('Schedule not found or inactive');
        }

        $bookedCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM bookings 
             WHERE schedule_id = ? AND booking_date = ? AND status = 'confirmed'",
            [$scheduleId, $date]
        );

        $waitlistCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM waitlist 
             WHERE schedule_id = ? AND booking_date = ? AND status = 'waiting'",
            [$scheduleId, $date]
        );

        return [
            'schedule_id' => $scheduleId,
            'bus_number' => $schedule['bus_number'],
            'schedule_type' => $schedule['schedule_type'],
            'departure_time' => $schedule['departure_time'],
            'boarding_time' => $schedule['boarding_time'],
            'capacity' => (int)$schedule['capacity'],
            'booked_count' => (int)$bookedCount,
            'available_count' => (int)$schedule['capacity'] - (int)$bookedCount,
            'waitlist_count' => (int)$waitlistCount,
            'can_book' => (int)$bookedCount < (int)$schedule['capacity'],
            'booking_cutoff' => $this->getBookingCutoffTime($schedule, $date)
        ];
    }

    public function bookSlot(string $workerId, int $scheduleId, string $date): array
    {
        $this->db->beginTransaction();

        try {
            // Validate booking constraints
            $this->validateBooking($workerId, $scheduleId, $date);

            // Get user
            $user = $this->db->fetchOne("SELECT * FROM users WHERE worker_id = ?", [$workerId]);
            if (!$user) {
                throw new \Exception('User not found');
            }

            // Check availability with row locking
            $schedule = $this->db->fetchOne(
                "SELECT s.*, b.capacity FROM schedules s 
                 JOIN buses b ON s.bus_id = b.id 
                 WHERE s.id = ? FOR UPDATE",
                [$scheduleId]
            );

            $currentBookings = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM bookings 
                 WHERE schedule_id = ? AND booking_date = ? AND status = 'confirmed' FOR UPDATE",
                [$scheduleId, $date]
            );

            if ($currentBookings >= $schedule['capacity']) {
                // Add to waitlist
                $position = $this->addToWaitlist($user['id'], $scheduleId, $date);
                $this->db->commit();

                $this->emailService->sendWaitlistNotification($user, $schedule, $date, $position);

                return [
                    'status' => 'waitlisted',
                    'message' => 'Bus is full. You have been added to waitlist.',
                    'position' => $position,
                    'booking_id' => null
                ];
            }

            // Create booking
            $bookingId = $this->db->insert('bookings', [
                'user_id' => $user['id'],
                'schedule_id' => $scheduleId,
                'booking_date' => $date,
                'status' => 'confirmed',
                'booking_time' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();

            // Send confirmation email
            $booking = $this->getBookingDetails($bookingId);
            $this->emailService->sendBookingConfirmation($user, $booking);

            // Update real-time availability
            $this->updateRealTimeAvailability($scheduleId, $date);

            return [
                'status' => 'confirmed',
                'message' => 'Booking confirmed successfully',
                'booking_id' => $bookingId,
                'position' => null
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function cancelBooking(string $workerId, int $scheduleId, string $date): array
    {
        $this->db->beginTransaction();

        try {
            $user = $this->db->fetchOne("SELECT * FROM users WHERE worker_id = ?", [$workerId]);
            if (!$user) {
                throw new \Exception('User not found');
            }

            // Find and cancel booking
            $booking = $this->db->fetchOne(
                "SELECT * FROM bookings 
                 WHERE user_id = ? AND schedule_id = ? AND booking_date = ? AND status = 'confirmed'",
                [$user['id'], $scheduleId, $date]
            );

            if ($booking) {
                // Cancel the booking
                $this->db->update('bookings', 
                    ['status' => 'cancelled', 'cancelled_time' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$booking['id']]
                );

                // Check waitlist and promote next person
                $this->promoteFromWaitlist($scheduleId, $date);
                
                $message = 'Booking cancelled successfully';
                $this->emailService->sendCancellationConfirmation($user, $booking);
            } else {
                // Check if user is on waitlist
                $waitlistEntry = $this->db->fetchOne(
                    "SELECT * FROM waitlist 
                     WHERE user_id = ? AND schedule_id = ? AND booking_date = ? AND status = 'waiting'",
                    [$user['id'], $scheduleId, $date]
                );

                if (!$waitlistEntry) {
                    throw new \Exception('No booking or waitlist entry found');
                }

                // Cancel waitlist entry
                $this->db->update('waitlist',
                    ['status' => 'cancelled'],
                    'id = ?',
                    [$waitlistEntry['id']]
                );
                
                $message = 'Waitlist entry cancelled successfully';
            }

            $this->db->commit();

            // Update real-time availability
            $this->updateRealTimeAvailability($scheduleId, $date);

            return [
                'status' => 'cancelled',
                'message' => $message
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function getUserBookingStatus(string $workerId, int $scheduleId, string $date): array
    {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE worker_id = ?", [$workerId]);
        
        if (!$user) {
            return ['status' => 'no_booking', 'can_book' => true];
        }

        // Check for confirmed booking
        $booking = $this->db->fetchOne(
            "SELECT * FROM bookings 
             WHERE user_id = ? AND schedule_id = ? AND booking_date = ? AND status = 'confirmed'",
            [$user['id'], $scheduleId, $date]
        );

        if ($booking) {
            return [
                'status' => 'booked',
                'booking_id' => $booking['id'],
                'booking_time' => $booking['booking_time'],
                'can_cancel' => $this->canCancelBooking($booking, $scheduleId)
            ];
        }

        // Check waitlist
        $waitlistEntry = $this->db->fetchOne(
            "SELECT * FROM waitlist 
             WHERE user_id = ? AND schedule_id = ? AND booking_date = ? AND status = 'waiting'",
            [$user['id'], $scheduleId, $date]
        );

        if ($waitlistEntry) {
            return [
                'status' => 'waitlisted',
                'position' => $waitlistEntry['position'],
                'can_cancel' => true
            ];
        }

        return ['status' => 'no_booking', 'can_book' => true];
    }

    public function getMyBookings(int $userId, string $startDate = null, string $endDate = null): array
    {
        $whereClause = "b.user_id = ?";
        $params = [$userId];

        if ($startDate) {
            $whereClause .= " AND b.booking_date >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND b.booking_date <= ?";
            $params[] = $endDate;
        }

        $bookings = $this->db->fetchAll(
            "SELECT b.*, s.schedule_type, s.departure_time, s.boarding_time,
                    bus.bus_number, bus.capacity
             FROM bookings b
             JOIN schedules s ON b.schedule_id = s.id
             JOIN buses bus ON s.bus_id = bus.id
             WHERE {$whereClause}
             ORDER BY b.booking_date DESC, s.departure_time",
            $params
        );

        return $bookings;
    }

    private function validateBooking(string $workerId, int $scheduleId, string $date): void
    {
        // Check if booking date is valid (not in past, not too far in future)
        $bookingDate = new \DateTime($date);
        $now = new \DateTime();
        $maxAdvanceDate = (new \DateTime())->modify('+' . MAX_ADVANCE_BOOKING_DAYS . ' days');

        if ($bookingDate < $now->setTime(0, 0, 0)) {
            throw new \Exception('Cannot book for past dates');
        }

        if ($bookingDate > $maxAdvanceDate) {
            throw new \Exception('Cannot book more than ' . MAX_ADVANCE_BOOKING_DAYS . ' days in advance');
        }

        // Check if booking cutoff time has passed
        $schedule = $this->db->fetchOne("SELECT * FROM schedules WHERE id = ?", [$scheduleId]);
        $cutoffTime = $this->getBookingCutoffTime($schedule, $date);
        
        if (time() > $cutoffTime) {
            throw new \Exception('Booking cutoff time has passed');
        }

        // Check if user already has a booking for this schedule and date
        $user = $this->db->fetchOne("SELECT * FROM users WHERE worker_id = ?", [$workerId]);
        if ($user) {
            $existingBooking = $this->db->fetchOne(
                "SELECT * FROM bookings 
                 WHERE user_id = ? AND schedule_id = ? AND booking_date = ? AND status IN ('confirmed')",
                [$user['id'], $scheduleId, $date]
            );

            if ($existingBooking) {
                throw new \Exception('You already have a booking for this schedule');
            }

            // Check if user is already on waitlist
            $waitlistEntry = $this->db->fetchOne(
                "SELECT * FROM waitlist 
                 WHERE user_id = ? AND schedule_id = ? AND booking_date = ? AND status = 'waiting'",
                [$user['id'], $scheduleId, $date]
            );

            if ($waitlistEntry) {
                throw new \Exception('You are already on the waitlist for this schedule');
            }
        }
    }

    private function addToWaitlist(int $userId, int $scheduleId, string $date): int
    {
        // Get next position
        $position = $this->db->fetchColumn(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM waitlist 
             WHERE schedule_id = ? AND booking_date = ? AND status = 'waiting'",
            [$scheduleId, $date]
        );

        $expiresAt = date('Y-m-d H:i:s', time() + (WAITLIST_EXPIRY_HOURS * 3600));

        $this->db->insert('waitlist', [
            'user_id' => $userId,
            'schedule_id' => $scheduleId,
            'booking_date' => $date,
            'position' => $position,
            'status' => 'waiting',
            'expires_at' => $expiresAt
        ]);

        return (int)$position;
    }

    private function promoteFromWaitlist(int $scheduleId, string $date): void
    {
        $nextWaitlistEntry = $this->db->fetchOne(
            "SELECT w.*, u.worker_id, u.name, u.email 
             FROM waitlist w
             JOIN users u ON w.user_id = u.id
             WHERE w.schedule_id = ? AND w.booking_date = ? AND w.status = 'waiting'
             ORDER BY w.position ASC
             LIMIT 1",
            [$scheduleId, $date]
        );

        if ($nextWaitlistEntry) {
            // Create booking
            $bookingId = $this->db->insert('bookings', [
                'user_id' => $nextWaitlistEntry['user_id'],
                'schedule_id' => $scheduleId,
                'booking_date' => $date,
                'status' => 'confirmed',
                'booking_time' => date('Y-m-d H:i:s')
            ]);

            // Update waitlist entry
            $this->db->update('waitlist',
                ['status' => 'converted'],
                'id = ?',
                [$nextWaitlistEntry['id']]
            );

            // Send notification
            $booking = $this->getBookingDetails($bookingId);
            $this->emailService->sendWaitlistConversionNotification($nextWaitlistEntry, $booking);
        }
    }

    private function getBookingDetails(int $bookingId): array
    {
        return $this->db->fetchOne(
            "SELECT b.*, s.schedule_type, s.departure_time, s.boarding_time,
                    bus.bus_number
             FROM bookings b
             JOIN schedules s ON b.schedule_id = s.id
             JOIN buses bus ON s.bus_id = bus.id
             WHERE b.id = ?",
            [$bookingId]
        );
    }

    private function canCancelBooking(array $booking, int $scheduleId): bool
    {
        $schedule = $this->db->fetchOne("SELECT * FROM schedules WHERE id = ?", [$scheduleId]);
        $cutoffTime = $this->getBookingCutoffTime($schedule, $booking['booking_date']);
        
        return time() < $cutoffTime;
    }

    private function getBookingCutoffTime(array $schedule, string $date): int
    {
        $departureDateTime = new \DateTime($date . ' ' . $schedule['departure_time']);
        $cutoffDateTime = $departureDateTime->modify('-' . BOOKING_CUTOFF_MINUTES . ' minutes');
        return $cutoffDateTime->getTimestamp();
    }

    private function updateRealTimeAvailability(int $scheduleId, string $date): void
    {
        $availability = $this->getAvailability($scheduleId, $date);
        $key = "availability:{$scheduleId}:{$date}";
        $this->redis->setex($key, 300, json_encode($availability)); // Cache for 5 minutes
    }
}